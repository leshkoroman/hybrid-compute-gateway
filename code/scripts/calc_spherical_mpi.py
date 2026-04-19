import numpy as np
import scipy.linalg as la
import time
import csv
import os
import sys
import argparse
from numba import njit, prange, get_num_threads

# ======================================================================
# ФІКСОВАНІ ПАРАМЕТРИ ОБЧИСЛЕНЬ
# ======================================================================
N_LEVELS_SAVE = 70   # Скільки рівнів зберігати у CSV
N_CUT_SQ = 45        # Параметр зрізу базису плоских хвиль
HBAR2_2M0 = 38.1     # ℏ²/2m₀, МеВ·нм²

# ======================================================================
# JIT-функції
# ======================================================================

@njit(cache=True)
def calc_gamma_tilde_jit(g2, g3):
    return (2.0 * g2 + 3.0 * g3) / 5.0

@njit(cache=True)
def shape_function_ft_jit(q_norm, R, Vol):
    if q_norm < 1e-10:
        return (4.0 * np.pi / 3.0) * (R**3) / Vol
    return (4.0 * np.pi / (Vol * q_norm**3)) * (
        np.sin(q_norm * R) - q_norm * R * np.cos(q_norm * R))

@njit(cache=True)
def param_ft_jit(q_norm, param_QD, param_MAT, R, Vol):
    delta_q0 = 1.0 if q_norm < 1e-10 else 0.0
    S_q = shape_function_ft_jit(q_norm, R, Vol)
    return param_MAT * delta_q0 + (param_QD - param_MAT) * S_q

@njit(parallel=True, cache=True)
def build_hamiltonian(k_vecs, J_anticomm_arr, I_4_arr, R, Vol,
                      g1_QD, g1_MAT, gt_QD, gt_MAT, V_Q, V_M, hbar2_2m0):
    Nw    = k_vecs.shape[0]
    H_mat = np.zeros((4 * Nw, 4 * Nw), dtype=np.complex128)

    for i in prange(Nw):
        for j in range(Nw):
            qx = k_vecs[i, 0] - k_vecs[j, 0]
            qy = k_vecs[i, 1] - k_vecs[j, 1]
            qz = k_vecs[i, 2] - k_vecs[j, 2]
            q_norm = np.sqrt(qx**2 + qy**2 + qz**2)

            g1_q      = param_ft_jit(q_norm, g1_QD, g1_MAT, R, Vol)
            g_tilde_q = param_ft_jit(q_norm, gt_QD, gt_MAT, R, Vol)
            V_q       = param_ft_jit(q_norm, V_Q,   V_M,    R, Vol)

            for m1 in range(4):
                for m2 in range(4):
                    T_val = 0.0 + 0.0j
                    for a in range(3):
                        for b in range(3):
                            delta_ab = 1.0 if a == b else 0.0
                            K_ab = (g1_q * delta_ab * I_4_arr[m1, m2] -
                                    2.0 * g_tilde_q * (
                                        0.5 * J_anticomm_arr[a, b, m1, m2] -
                                        1.25 * delta_ab * I_4_arr[m1, m2]))
                            T_val += k_vecs[i, a] * k_vecs[j, b] * K_ab

                    H_mat[i*4 + m1, j*4 + m2] = T_val * hbar2_2m0 + V_q * I_4_arr[m1, m2]

    return H_mat

# ======================================================================
# Незмінні спінові матриці
# ======================================================================

def _build_spin_matrices():
    J_z    = np.diag([1.5, 0.5, -0.5, -1.5])
    J_plus = np.array([[0, np.sqrt(3), 0, 0],
                       [0, 0, 2, 0],
                       [0, 0, 0, np.sqrt(3)],
                       [0, 0, 0, 0]], dtype=complex)
    J_minus = J_plus.T
    J_x =  0.5   * (J_plus + J_minus)
    J_y = -0.5j  * (J_plus - J_minus)
    Jm  = [J_x, J_y, J_z]
    J_ac = np.zeros((3, 3, 4, 4), dtype=np.complex128)
    for a in range(3):
        for b in range(3):
            J_ac[a, b] = Jm[a] @ Jm[b] + Jm[b] @ Jm[a]
    return J_ac, np.eye(4, dtype=np.complex128)

# ======================================================================
# Допоміжні функції
# ======================================================================

def make_k_vectors(R_nm):
    """Генерує k-вектори для суперкомірки L = 5·R."""
    L     = 5.0 * R_nm
    n_max = int(np.sqrt(N_CUT_SQ))
    k_list = []
    for nx in range(-n_max, n_max + 1):
        for ny in range(-n_max, n_max + 1):
            for nz in range(-n_max, n_max + 1):
                if nx**2 + ny**2 + nz**2 <= N_CUT_SQ:
                    k_list.append([nx, ny, nz])
    return np.array(k_list, dtype=np.float64) * (2.0 * np.pi / L)

def save_results(eigenvalues, eigenvectors, output_dir, n_levels, R_nm, save_wf=True):
    """Дописує рядок у CSV і опціонально зберігає хвильові функції у підпапку."""
    os.makedirs(output_dir, exist_ok=True)
    n_save = min(n_levels, len(eigenvalues))

    # CSV — один рядок на радіус
    csv_path  = os.path.join(output_dir, "energies_vs_R.csv")
    write_hdr = not os.path.exists(csv_path)
    
    with open(csv_path, "a", newline="", encoding="utf-8") as f:
        writer = csv.writer(f)
        if write_hdr:
            writer.writerow(["R_nm"] + [f"E{i}_meV" for i in range(n_save)])
        writer.writerow([f"{R_nm:.4f}"] +
                        [f"{eigenvalues[i]:.6f}" for i in range(n_save)])

    # Хвильові функції зберігаємо лише для ЦІЛЬОВОГО радіуса (якщо save_wf = True)
    if save_wf and eigenvectors is not None:
        wf_dir = os.path.join(output_dir, "wavefunctions", f"R_{R_nm:.4f}_nm")
        os.makedirs(wf_dir, exist_ok=True)
        for i in range(n_save):
            filename = f"level_{i:03d}_E_{eigenvalues[i]:.6f}_meV.npy"
            np.save(os.path.join(wf_dir, filename), eigenvectors[:, i])

# ======================================================================
# MAIN (Точка входу)
# ======================================================================

def main():
    # 1. Прийом аргументів від Laravel
    parser = argparse.ArgumentParser(description="Сферична квантова точка Латтінжера")
    parser.add_argument('--calc_id', type=str, required=False)
    parser.add_argument('--output_dir', type=str, required=True)
    
    # Параметри ядра (Core)
    parser.add_argument('--core_v_pot', type=float, required=True)
    parser.add_argument('--core_g1', type=float, required=True)
    parser.add_argument('--core_g2', type=float, required=True)
    parser.add_argument('--core_g3', type=float, required=True)
    
    # Параметри матриці (Matrix/Shell)
    parser.add_argument('--matrix_v_pot', type=float, required=True)
    parser.add_argument('--matrix_g1', type=float, required=True)
    parser.add_argument('--matrix_g2', type=float, required=True)
    parser.add_argument('--matrix_g3', type=float, required=True)
    
    # Геометрія (Цільовий радіус)
    parser.add_argument('--radius', type=float, required=True)

    args = parser.parse_args()

    # 2. Розрахунок похідних фізичних параметрів
    GT_QD  = calc_gamma_tilde_jit(args.core_g2, args.core_g3)
    GT_MAT = calc_gamma_tilde_jit(args.matrix_g2, args.matrix_g3)
    J_ANTICOMM, I_4 = _build_spin_matrices()

    # 3. Адаптивний крок для графіка (Energy vs R)
    target_R = args.radius
    
    if target_R <= 5.0:
        step = 0.5
    elif target_R <= 20.0:
        step = 1.0
    elif target_R <= 50.0:
        step = 2.0
    else:
        step = 5.0

    # Генеруємо масив точок для графіка, починаючи від найменшого (напр. step) до target_R
    R_values = np.arange(step, target_R + 1e-9, step)
    
    # Обов'язково додаємо цільовий радіус у кінець, якщо його там ще немає
    if len(R_values) == 0 or abs(R_values[-1] - target_R) > 1e-5:
        R_values = np.append(R_values, target_R)
        
    R_values = np.unique(np.round(R_values, 4)) # Очищаємо від дублікатів і похибок float

    print("=" * 60)
    print(f"  Сферична КТ | Цільовий радіус: {target_R} нм")
    print(f"  Крок розрахунку точок: {step} нм")
    print(f"  Точок для графіка: {len(R_values)}")
    print(f"  Збереження результатів: {args.output_dir}")
    print("=" * 60)

    # 4. Прогрів JIT компілятора
    print("\n[INFO] Компіляція JIT (одноразово)...")
    k_warm = make_k_vectors(R_values[0])[:10]
    build_hamiltonian(k_warm, J_ANTICOMM, I_4,
                      R_values[0], (5.0 * R_values[0])**3,
                      args.core_g1, args.matrix_g1, GT_QD, GT_MAT,
                      args.core_v_pot, args.matrix_v_pot, HBAR2_2M0)
    print("[INFO] JIT готовий.\n")

    t_total = time.time()

    # 5. Основний цикл обчислень
    for step_idx, R_nm in enumerate(R_values, 1):
        print(f"[{step_idx:2d}/{len(R_values)}] Розрахунок для R = {R_nm:.4f} нм...")

        L     = 5.0 * R_nm
        Omega = L**3
        k_vecs = make_k_vectors(R_nm)
        
        # Побудова гамільтоніана
        H  = build_hamiltonian(k_vecs, J_ANTICOMM, I_4, R_nm, Omega,
                               args.core_g1, args.matrix_g1, GT_QD, GT_MAT,
                               args.core_v_pot, args.matrix_v_pot, HBAR2_2M0)

        # Діагоналізація
        eigenvalues, eigenvectors = la.eigh(H)

        # Збереження
        if step_idx == len(R_values):
            # Для останньої (цільової) точки зберігаємо і енергії, і хвильові функції
            save_results(eigenvalues, eigenvectors, args.output_dir, N_LEVELS_SAVE, R_nm, save_wf=True)
        else:
            # Для проміжних точок графіка зберігаємо лише енергії у CSV
            save_results(eigenvalues, None, args.output_dir, N_LEVELS_SAVE, R_nm, save_wf=False)

    # 6. Завершення
    elapsed = time.time() - t_total
    print(f"\n{'='*60}")
    print(f"  Розрахунок завершено успішно за {elapsed/60:.1f} хв")
    print(f"  Усі дані збережено до: {args.output_dir}")
    print("=" * 60)

if __name__ == "__main__":
    main()